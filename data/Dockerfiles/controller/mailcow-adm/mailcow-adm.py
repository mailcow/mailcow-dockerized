#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import argparse
import sys

from models.AliasModel import AliasModel
from models.MailboxModel import MailboxModel
from models.SyncjobModel import SyncjobModel
from models.CalendarModel import CalendarModel
from models.MailerModel import MailerModel
from models.AddressbookModel import AddressbookModel
from models.MaildirModel import MaildirModel
from models.DomainModel import DomainModel
from models.DomainadminModel import DomainadminModel
from models.StatusModel import StatusModel

from modules.Utils import Utils




def main():
    utils = Utils()

    model_map = {
        MailboxModel.parser_command: MailboxModel,
        AliasModel.parser_command: AliasModel,
        SyncjobModel.parser_command: SyncjobModel,
        CalendarModel.parser_command: CalendarModel,
        AddressbookModel.parser_command: AddressbookModel,
        MailerModel.parser_command: MailerModel,
        MaildirModel.parser_command: MaildirModel,
        DomainModel.parser_command: DomainModel,
        DomainadminModel.parser_command: DomainadminModel,
        StatusModel.parser_command: StatusModel
    }

    parser = argparse.ArgumentParser(description="mailcow Admin Tool")
    subparsers = parser.add_subparsers(dest="command", required=True)

    for model in model_map.values():
        model.add_parser(subparsers)

    args = parser.parse_args()


    for cmd, model_cls in model_map.items():
        if args.command == cmd and model_cls.has_required_args(args):
            instance = model_cls(**vars(args))
            action = getattr(instance, args.object, None)
            if callable(action):
                res = action()
                utils.pprint(res)
                sys.exit(0)

    parser.print_help()


if __name__ == "__main__":
    main()
