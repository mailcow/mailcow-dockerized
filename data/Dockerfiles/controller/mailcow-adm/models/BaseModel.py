class BaseModel:
    parser_command = ""
    required_args = {}

    @classmethod
    def has_required_args(cls, args):
        """
        Validate that all required arguments are present.
        """
        object_name = args.object if hasattr(args, "object") else args.get("object")
        required_lists = cls.required_args.get(object_name, False)

        if not required_lists:
            return False

        for required_set in required_lists:
            result = True
            for required_args in required_set:
                if isinstance(args, dict):
                    if not args.get(required_args):
                        result = False
                        break
                elif not hasattr(args, required_args):
                    result = False
                    break
            if result:
                break

        if not result:
            print(f"Required arguments for '{object_name}': {required_lists}")
        return result

    @classmethod
    def add_parser(cls, subparsers):
        pass
