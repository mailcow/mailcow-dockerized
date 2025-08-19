from modules.Sogo import Sogo
from models.BaseModel import BaseModel

class AddressbookModel(BaseModel):
    parser_command = "addressbook"
    required_args = {
        "add": [["username", "name"]],
        "delete": [["username", "name"]],
        "get":  [["username", "name"]],
        "set_acl": [["username", "name", "sharee_email", "acl"]],
        "get_acl": [["username", "name"]],
        "delete_acl": [["username", "name", "sharee_email"]],
        "add_contact": [["username", "name", "contact_name", "contact_email", "type"]],
        "delete_contact": [["username", "name", "contact_name"]],
    }

    def __init__(
        self,
        username=None,
        name=None,
        sharee_email=None,
        acl=None,
        subscribe=None,
        ics=None,
        contact_name=None,
        contact_email=None,
        type=None,
        **kwargs
    ):
        self.sogo = Sogo(username)

        self.name = name
        self.acl = acl
        self.sharee_email = sharee_email
        self.subscribe = subscribe
        self.ics = ics
        self.contact_name = contact_name
        self.contact_email = contact_email
        self.type = type

    def add(self):
        """
        Add a new addressbook.
        :return: Response from SOGo API.
        """
        return self.sogo.addAddressbook(self.name)

    def set_acl(self):
        """
        Set ACL for the addressbook.
        :return: Response from SOGo API.
        """
        addressbook_id = self.sogo.getAddressbookIdByName(self.name)
        if not addressbook_id:
            print(f"Addressbook '{self.name}' not found for user '{self.username}'.")
            return None
        return self.sogo.setAddressbookACL(addressbook_id, self.sharee_email, self.acl, self.subscribe)

    def delete_acl(self):
        """
        Delete the addressbook ACL.
        :return: Response from SOGo API.
        """
        addressbook_id = self.sogo.getAddressbookIdByName(self.name)
        if not addressbook_id:
            print(f"Addressbook '{self.name}' not found for user '{self.username}'.")
            return None
        return self.sogo.deleteAddressbookACL(addressbook_id, self.sharee_email)

    def get_acl(self):
        """
        Get the ACL for the addressbook.
        :return: Response from SOGo API.
        """
        addressbook_id = self.sogo.getAddressbookIdByName(self.name)
        if not addressbook_id:
            print(f"Addressbook '{self.name}' not found for user '{self.username}'.")
            return None
        return self.sogo.getAddressbookACL(addressbook_id)

    def add_contact(self):
        """
        Add a new contact to the addressbook.
        :return: Response from SOGo API.
        """
        addressbook_id = self.sogo.getAddressbookIdByName(self.name)
        if not addressbook_id:
            print(f"Addressbook '{self.name}' not found for user '{self.username}'.")
            return None
        if self.type == "card":
            return self.sogo.addAddressbookContact(addressbook_id, self.contact_name, self.contact_email)
        elif self.type == "list":
            return self.sogo.addAddressbookContactList(addressbook_id, self.contact_name, self.contact_email)

    def delete_contact(self):
        """
        Delete a contact or contactlist from the addressbook.
        :return: Response from SOGo API.
        """
        addressbook_id = self.sogo.getAddressbookIdByName(self.name)
        if not addressbook_id:
            print(f"Addressbook '{self.name}' not found for user '{self.username}'.")
            return None
        return self.sogo.deleteAddressbookItem(addressbook_id, self.contact_name)

    def get(self):
        """
        Retrieve addressbooks list.
        :return: Response from SOGo API.
        """
        return self.sogo.getAddressbookList()

    def delete(self):
        """
        Delete the addressbook.
        :return: Response from SOGo API.
        """

        addressbook_id = self.sogo.getAddressbookIdByName(self.name)
        if not addressbook_id:
            print(f"Addressbook '{self.name}' not found for user '{self.username}'.")
            return None
        return self.sogo.deleteAddressbook(addressbook_id)

    @classmethod
    def add_parser(cls, subparsers):
        parser = subparsers.add_parser(
            cls.parser_command,
            help="Manage addressbooks (add, delete, get, set_acl, get_acl, delete_acl, add_contact, delete_contact)"
        )
        parser.add_argument("object", choices=list(cls.required_args.keys()), help="Action to perform: add, delete, get, set_acl, get_acl, delete_acl, add_contact, delete_contact")
        parser.add_argument("--username", required=True, help="Username of the addressbook owner (e.g. user@example.com)")
        parser.add_argument("--name", help="Addressbook name")
        parser.add_argument("--sharee-email", help="Email address to share the addressbook with")
        parser.add_argument("--acl", help="ACL rights for the sharee (e.g. r, w, rw)")
        parser.add_argument("--subscribe", action='store_true', help="Subscribe the sharee to the addressbook")
        parser.add_argument("--contact-name", help="Name of the contact or contactlist to add or delete")
        parser.add_argument("--contact-email", help="Email address of the contact to add")
        parser.add_argument("--type", choices=["card", "list"], help="Type of contact to add: card (single contact) or list (distribution list)")

