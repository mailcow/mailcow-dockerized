from modules.Dovecot import Dovecot
from models.BaseModel import BaseModel

class MaildirModel(BaseModel):
    parser_command = "maildir"
    required_args = {
        "encrypt": [],
        "decrypt": [],
        "restore": [["username", "item"], ["list"]]
    }

    def __init__(
        self,
        username=None,
        source=None,
        item=None,
        overwrite=None,
        list=None,
        **kwargs
    ):
        self.dovecot = Dovecot()

        for key, value in kwargs.items():
            setattr(self, key, value)

        self.username = username
        self.source = source
        self.item = item
        self.overwrite = overwrite
        self.list = list

    def encrypt(self):
        """
        Encrypt the maildir for the specified user or all.
        :return: Response from Dovecot.
        """
        return self.dovecot.encryptMaildir(self.source_dir, self.output_dir)

    def decrypt(self):
        """
        Decrypt the maildir for the specified user or all.
        :return: Response from Dovecot.
        """
        return self.dovecot.decryptMaildir(self.source_dir, self.output_dir)

    def restore(self):
        """
        Restore or List maildir data for the specified user.
        :return: Response from Dovecot.
        """
        if self.list:
            return self.dovecot.listDeletedMaildirs()
        return self.dovecot.restoreMaildir(self.username, self.item)


    @classmethod
    def add_parser(cls, subparsers):
        parser = subparsers.add_parser(
            cls.parser_command,
            help="Manage maildir (encrypt, decrypt, restore)"
        )
        parser.add_argument("object", choices=list(cls.required_args.keys()), help="Action to perform: encrypt, decrypt, restore")
        parser.add_argument("--item", help="Item to restore")
        parser.add_argument("--username", help="Username to restore the item to")
        parser.add_argument("--list", action="store_true", help="List items to restore")
        parser.add_argument("--source-dir", help="Path to the source maildir to import/encrypt/decrypt")
        parser.add_argument("--output-dir", help="Directory to store encrypted/decrypted files inside the Dovecot container")
