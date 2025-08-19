from modules.Sogo import Sogo
from models.BaseModel import BaseModel

class CalendarModel(BaseModel):
    parser_command = "calendar"
    required_args = {
        "add": [["username", "name"]],
        "delete": [["username", "name"]],
        "get":  [["username"]],
        "import_ics": [["username", "name", "ics"]],
        "set_acl": [["username", "name", "sharee_email", "acl"]],
        "get_acl": [["username", "name"]],
        "delete_acl": [["username", "name", "sharee_email"]],
    }

    def __init__(
        self,
        username=None,
        name=None,
        sharee_email=None,
        acl=None,
        subscribe=None,
        ics=None,
        **kwargs
    ):
        self.sogo = Sogo(username)

        self.name = name
        self.acl = acl
        self.sharee_email = sharee_email
        self.subscribe = subscribe
        self.ics = ics

    def add(self):
        """
        Add a new calendar.
        :return: Response from SOGo API.
        """
        return self.sogo.addCalendar(self.name)

    def delete(self):
        """
        Delete a calendar.
        :return: Response from SOGo API.
        """
        calendar_id = self.sogo.getCalendarIdByName(self.name)
        if not calendar_id:
            print(f"Calendar '{self.name}' not found for user '{self.username}'.")
            return None
        return self.sogo.deleteCalendar(calendar_id)

    def get(self):
        """
        Get the calendar details.
        :return: Response from SOGo API.
        """
        return self.sogo.getCalendar()

    def set_acl(self):
        """
        Set ACL for the calendar.
        :return: Response from SOGo API.
        """
        calendar_id = self.sogo.getCalendarIdByName(self.name)
        if not calendar_id:
            print(f"Calendar '{self.name}' not found for user '{self.username}'.")
            return None
        return self.sogo.setCalendarACL(calendar_id, self.sharee_email, self.acl, self.subscribe)

    def delete_acl(self):
        """
        Delete the calendar ACL.
        :return: Response from SOGo API.
        """
        calendar_id = self.sogo.getCalendarIdByName(self.name)
        if not calendar_id:
            print(f"Calendar '{self.name}' not found for user '{self.username}'.")
            return None
        return self.sogo.deleteCalendarACL(calendar_id, self.sharee_email)

    def get_acl(self):
        """
        Get the ACL for the calendar.
        :return: Response from SOGo API.
        """
        calendar_id = self.sogo.getCalendarIdByName(self.name)
        if not calendar_id:
            print(f"Calendar '{self.name}' not found for user '{self.username}'.")
            return None
        return self.sogo.getCalendarACL(calendar_id)

    def import_ics(self):
        """
        Import a calendar from an ICS file.
        :return: Response from SOGo API.
        """
        return self.sogo.importCalendar(self.name, self.ics)

    @classmethod
    def add_parser(cls, subparsers):
        parser = subparsers.add_parser(
            cls.parser_command,
            help="Manage calendars (add, delete, get, import_ics, set_acl, get_acl, delete_acl)"
        )
        parser.add_argument("object", choices=list(cls.required_args.keys()), help="Action to perform: add, delete, get, import_ics, set_acl, get_acl, delete_acl")
        parser.add_argument("--username", required=True, help="Username of the calendar owner (e.g. user@example.com)")
        parser.add_argument("--name", help="Calendar name")
        parser.add_argument("--ics", help="Path to ICS file for import")
        parser.add_argument("--sharee-email", help="Email address to share the calendar with")
        parser.add_argument("--acl", help="ACL rights for the sharee (e.g. r, w, rw)")
        parser.add_argument("--subscribe", action='store_true', help="Subscribe the sharee to the calendar")
