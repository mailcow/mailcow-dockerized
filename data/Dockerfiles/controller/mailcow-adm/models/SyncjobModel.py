from modules.Mailcow import Mailcow
from models.BaseModel import BaseModel

class SyncjobModel(BaseModel):
    parser_command = "syncjob"
    required_args = {
        "add": [["username", "host1", "port1", "user1", "password1", "enc1"]],
        "delete": [["id"]],
        "get":  [["username"]],
        "edit": [["id"]],
        "run": [["id"]]
    }

    def __init__(
        self,
        id=None,
        username=None,
        host1=None,
        port1=None,
        user1=None,
        password1=None,
        enc1=None,
        mins_interval=None,
        subfolder2=None,
        maxage=None,
        maxbytespersecond=None,
        timeout1=None,
        timeout2=None,
        exclude=None,
        custom_parameters=None,
        delete2duplicates=None,
        delete1=None,
        delete2=None,
        automap=None,
        skipcrossduplicates=None,
        subscribeall=None,
        active=None,
        force=None,
        **kwargs
    ):
        self.mailcow = Mailcow()

        for key, value in kwargs.items():
            setattr(self, key, value)

        self.id = id
        self.username = username
        self.host1 = host1
        self.port1 = port1
        self.user1 = user1
        self.password1 = password1
        self.enc1 = enc1
        self.mins_interval = mins_interval
        self.subfolder2 = subfolder2
        self.maxage = maxage
        self.maxbytespersecond = maxbytespersecond
        self.timeout1 = timeout1
        self.timeout2 = timeout2
        self.exclude = exclude
        self.custom_parameters = custom_parameters
        self.delete2duplicates = delete2duplicates
        self.delete1 = delete1
        self.delete2 = delete2
        self.automap = automap
        self.skipcrossduplicates = skipcrossduplicates
        self.subscribeall = subscribeall
        self.active = active
        self.force = force

    @classmethod
    def from_dict(cls, data):
        return cls(
            username=data.get("username"),
            host1=data.get("host1"),
            port1=data.get("port1"),
            user1=data.get("user1"),
            password1=data.get("password1"),
            enc1=data.get("enc1"),
            mins_interval=data.get("mins_interval", None),
            subfolder2=data.get("subfolder2", None),
            maxage=data.get("maxage", None),
            maxbytespersecond=data.get("maxbytespersecond", None),
            timeout1=data.get("timeout1", None),
            timeout2=data.get("timeout2", None),
            exclude=data.get("exclude", None),
            custom_parameters=data.get("custom_parameters", None),
            delete2duplicates=data.get("delete2duplicates", None),
            delete1=data.get("delete1", None),
            delete2=data.get("delete2", None),
            automap=data.get("automap", None),
            skipcrossduplicates=data.get("skipcrossduplicates", None),
            subscribeall=data.get("subscribeall", None),
            active=data.get("active", None),
        )

    def getAdd(self):
        """
        Get the sync job details as a dictionary for adding, sets default values.
        :return: Dictionary containing sync job details.
        """
        syncjob = {
            "username": self.username,
            "host1": self.host1,
            "port1": self.port1,
            "user1": self.user1,
            "password1": self.password1,
            "enc1": self.enc1,
            "mins_interval": self.mins_interval if self.mins_interval is not None else 20,
            "subfolder2": self.subfolder2 if self.subfolder2 is not None else "",
            "maxage": self.maxage if self.maxage is not None else 0,
            "maxbytespersecond": self.maxbytespersecond if self.maxbytespersecond is not None else 0,
            "timeout1": self.timeout1 if self.timeout1 is not None else 600,
            "timeout2": self.timeout2 if self.timeout2 is not None else 600,
            "exclude": self.exclude if self.exclude is not None else "(?i)spam|(?i)junk",
            "custom_parameters": self.custom_parameters if self.custom_parameters is not None else "",
            "delete2duplicates": 1 if self.delete2duplicates else 0,
            "delete1": 1 if self.delete1 else 0,
            "delete2": 1 if self.delete2 else 0,
            "automap": 1 if self.automap else 0,
            "skipcrossduplicates": 1 if self.skipcrossduplicates else 0,
            "subscribeall": 1 if self.subscribeall else 0,
            "active": 1 if self.active else 0
        }
        return {key: value for key, value in syncjob.items() if value is not None}

    def getEdit(self):
        """
        Get the sync job details as a dictionary for editing, sets no default values.
        :return: Dictionary containing sync job details.
        """
        syncjob = {
            "username": self.username,
            "host1": self.host1,
            "port1": self.port1,
            "user1": self.user1,
            "password1": self.password1,
            "enc1": self.enc1,
            "mins_interval": self.mins_interval,
            "subfolder2": self.subfolder2,
            "maxage": self.maxage,
            "maxbytespersecond": self.maxbytespersecond,
            "timeout1": self.timeout1,
            "timeout2": self.timeout2,
            "exclude": self.exclude,
            "custom_parameters": self.custom_parameters,
            "delete2duplicates": self.delete2duplicates,
            "delete1": self.delete1,
            "delete2": self.delete2,
            "automap": self.automap,
            "skipcrossduplicates": self.skipcrossduplicates,
            "subscribeall": self.subscribeall,
            "active": self.active
        }
        return {key: value for key, value in syncjob.items() if value is not None}

    def get(self):
        """
        Get the mailbox details from the mailcow API.
        :return: Response from the mailcow API.
        """
        return self.mailcow.getSyncjob(self.username)

    def delete(self):
        """
        Get the mailbox details from the mailcow API.
        :return: Response from the mailcow API.
        """
        return self.mailcow.deleteSyncjob(self.id)

    def add(self):
        """
        Get the mailbox details from the mailcow API.
        :return: Response from the mailcow API.
        """
        return self.mailcow.addSyncjob(self.getAdd())

    def edit(self):
        """
        Get the mailbox details from the mailcow API.
        :return: Response from the mailcow API.
        """
        return self.mailcow.editSyncjob(self.id, self.getEdit())

    def run(self):
        """
        Run the sync job.
        :return: Response from the mailcow API.
        """
        return self.mailcow.runSyncjob(self.id, force=self.force)

    @classmethod
    def add_parser(cls, subparsers):
        parser = subparsers.add_parser(
            cls.parser_command,
            help="Manage sync jobs (add, delete, get, edit)"
        )
        parser.add_argument("object", choices=list(cls.required_args.keys()), help="Action to perform: add, delete, get, edit")
        parser.add_argument("--id", help="Syncjob object ID (required for edit, delete, run)")
        parser.add_argument("--username", help="Target mailbox username (e.g. user@example.com)")
        parser.add_argument("--host1", help="Source IMAP server hostname")
        parser.add_argument("--port1", help="Source IMAP server port")
        parser.add_argument("--user1", help="Source IMAP account username")
        parser.add_argument("--password1", help="Source IMAP account password")
        parser.add_argument("--enc1", choices=["PLAIN", "SSL", "TLS"], help="Encryption for source server connection")
        parser.add_argument("--mins-interval", help="Sync interval in minutes (default: 20)")
        parser.add_argument("--subfolder2", help="Destination subfolder (default: empty)")
        parser.add_argument("--maxage", help="Maximum mail age in days (default: 0 = unlimited)")
        parser.add_argument("--maxbytespersecond", help="Maximum bandwidth in bytes/sec (default: 0 = unlimited)")
        parser.add_argument("--timeout1", help="Timeout for source server in seconds (default: 600)")
        parser.add_argument("--timeout2", help="Timeout for destination server in seconds (default: 600)")
        parser.add_argument("--exclude", help="Regex pattern to exclude folders (default: (?i)spam|(?i)junk)")
        parser.add_argument("--custom-parameters", help="Additional imapsync parameters")
        parser.add_argument("--delete2duplicates", choices=["1", "0"], help="Delete duplicates on destination (1 = yes, 0 = no)")
        parser.add_argument("--del1", choices=["1", "0"], help="Delete mails on source after sync (1 = yes, 0 = no)")
        parser.add_argument("--del2", choices=["1", "0"], help="Delete mails on destination after sync (1 = yes, 0 = no)")
        parser.add_argument("--automap", choices=["1", "0"], help="Enable folder automapping (1 = yes, 0 = no)")
        parser.add_argument("--skipcrossduplicates", choices=["1", "0"], help="Skip cross-account duplicates (1 = yes, 0 = no)")
        parser.add_argument("--subscribeall", choices=["1", "0"], help="Subscribe to all folders (1 = yes, 0 = no)")
        parser.add_argument("--active", choices=["1", "0"], help="Activate syncjob (1 = yes, 0 = no)")
        parser.add_argument("--force", action="store_true", help="Force the syncjob to run even if it is not active")

