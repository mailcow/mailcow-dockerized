from modules.Mailcow import Mailcow
from models.BaseModel import BaseModel

class StatusModel(BaseModel):
    parser_command = "status"
    required_args = {
        "version": [[]],
        "vmail": [[]],
        "containers":  [[]]
    }

    def __init__(
        self,
        **kwargs
    ):
        self.mailcow = Mailcow()

    def version(self):
        """
        Get the version of the mailcow instance.
        :return: Response from the mailcow API.
        """
        return self.mailcow.getStatusVersion()

    def vmail(self):
        """
        Get the vmail details from the mailcow API.
        :return: Response from the mailcow API.
        """
        return self.mailcow.getStatusVmail()

    def containers(self):
        """
        Get the status of containers in the mailcow instance.
        :return: Response from the mailcow API.
        """
        return self.mailcow.getStatusContainers()

    @classmethod
    def add_parser(cls, subparsers):
        parser = subparsers.add_parser(
            cls.parser_command,
            help="Get information about mailcow (version, vmail, containers)"
        )
        parser.add_argument("object", choices=list(cls.required_args.keys()), help="Action to perform: version, vmail, containers")
